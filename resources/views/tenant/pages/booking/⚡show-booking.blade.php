{{-- resources/views/tenant/pages/booking/⚡show-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

new
#[Layout('tenant::layouts.app')]
#[Title('Booking Details')]
class extends Component
{
    use ChecksTenantPermissions;

    /** Bound from route. Auto-locked (Eloquent model). */
    public Booking $booking;

    // ── Payment modal state ──
    public bool $showPaymentModal = false;
    public string $paymentMethod = 'cash';   // 'cash' | 'qr'

    // ── QR modal state ──
    public bool $showQrModal = false;
    public ?string $qrImage = null;
    public ?string $qrPaymentIntentId = null;
    public ?string $qrExpiresAt = null;
    public ?string $qrError = null;
    public float $qrAmount = 0.0;

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

    // ─────────────────────────────────────────────────────────
    //  Payment helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Amount the customer still owes NOW.
     *
     *   • pending reservation → 20% reservation fee minus any paid rows
     *   • anything else       → total minus paid
     */
    public function amountDueFor(): float
    {
        $total = (float) $this->booking->total_amount;
        $paid  = $this->paidAmount;

        if (
            $this->booking->booking_type === Booking::TYPE_RESERVATION
            && $this->booking->status === Booking::STATUS_PENDING
        ) {
            $fee = round($total * 0.20, 2);
            return max(0.0, $fee - $paid);
        }

        return max(0.0, $total - $paid);
    }

    public function canCollectPayment(): bool
    {
        if (in_array($this->booking->status, [
            Booking::STATUS_CANCELLED,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CHECKED_IN,
        ], true)) {
            return false;
        }

        return $this->amountDueFor() > 0;
    }

    protected function paymentTypeFor(): string
    {
        if (
            $this->booking->booking_type === Booking::TYPE_RESERVATION
            && $this->booking->status === Booking::STATUS_PENDING
        ) {
            return Payment::TYPE_RESERVATION;
        }

        return Payment::TYPE_FULL;
    }

    /**
     * Recompute the booking's status after a paid row exists. Mirrors
     * PayMongoService::finalizePayment()'s branching, plus a corrective
     * pass: any booking whose paid sum ≥ total lands on CONFIRMED,
     * regardless of what the service may have set based on payment_type.
     */
    protected function applyPaymentTransition(): void
    {
        $total = (float) $this->booking->total_amount;

        $paid = (float) Payment::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('booking_id', $this->booking->id)
            ->where('payment_status', 'paid')
            ->sum('amount');

        if ($paid >= $total) {
            $this->booking->update(['status' => Booking::STATUS_CONFIRMED]);
            return;
        }

        if (
            $this->booking->booking_type === Booking::TYPE_RESERVATION
            && $this->booking->status === Booking::STATUS_PENDING
        ) {
            $fee = round($total * 0.20, 2);
            if ($paid >= $fee) {
                $this->booking->update(['status' => Booking::STATUS_RESERVED]);
            }
        }
    }

    protected function refreshBookingState(): void
    {
        $this->booking->refresh();
        $this->booking->unsetRelation('payments');

        unset($this->paidAmount);
        unset($this->balance);
        unset($this->isSettled);
    }

    // ─────────────────────────────────────────────────────────
    //  Payment modal actions
    // ─────────────────────────────────────────────────────────

    public function openPaymentModal(): void
    {
        if (! $this->canCollectPayment()) {
            return;
        }

        $this->paymentMethod    = 'cash';
        $this->showPaymentModal = true;
        $this->qrError          = null;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->qrError          = null;
    }

    public function submitCashPayment(): void
    {
        $this->requirePermission('manage payments');

        if (! $this->canCollectPayment()) {
            $this->closePaymentModal();
            return;
        }

        $amount = $this->amountDueFor();
        if ($amount <= 0) {
            session()->flash('error', 'Nothing left to collect on this booking.');
            $this->closePaymentModal();
            return;
        }

        Payment::create([
            'tenant_id'      => $this->booking->tenant_id,
            'booking_id'     => $this->booking->id,
            'amount'         => $amount,
            'payment_method' => 'cash',
            'payment_type'   => $this->paymentTypeFor(),
            'payment_status' => 'paid',
            'paid_at'        => now(),
        ]);

        $this->refreshBookingState();
        $this->applyPaymentTransition();

        $this->closePaymentModal();
        session()->flash('message', 'Cash payment of ₱' . number_format($amount, 2) . ' recorded.');
    }

    public function submitQrPayment(): void
    {
        $this->requirePermission('manage payments');

        if (! $this->canCollectPayment()) {
            $this->closePaymentModal();
            return;
        }

        $amount = $this->amountDueFor();
        if ($amount <= 0) {
            session()->flash('error', 'Nothing left to collect on this booking.');
            $this->closePaymentModal();
            return;
        }

        $payMongo = app(PayMongoService::class);

        $intent = $payMongo->createQrPhPaymentIntent(
            $amount,
            'Booking ' . $this->booking->booking_reference,
            [
                'booking_id' => (string) $this->booking->id,
                'tenant_id'  => (string) $this->booking->tenant_id,
            ],
        );

        if (! $intent) {
            Log::error('[show-booking] QR intent creation returned null', [
                'booking_id' => $this->booking->id,
            ]);
            $this->qrError = 'PayMongo rejected the payment intent. Check storage/logs/laravel.log.';
            return;
        }

        $qr = $payMongo->attachQrPhPaymentMethod(
            $intent['id'],
            $intent['client_key'],
            route('tenant.bookings.show', ['booking' => $this->booking->id]),
        );

        if (! $qr) {
            Log::error('[show-booking] QR attach returned null', [
                'booking_id' => $this->booking->id,
                'intent_id'  => $intent['id'],
            ]);
            $this->qrError = 'PayMongo rejected the QR attach call. Check storage/logs/laravel.log.';
            return;
        }

        try {
            Payment::create([
                'tenant_id'        => $this->booking->tenant_id,
                'booking_id'       => $this->booking->id,
                'amount'           => $amount,
                'payment_method'   => 'qr',
                'payment_type'     => $this->paymentTypeFor(),
                'payment_status'   => 'pending',
                'reference_number' => $qr['payment_intent_id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[show-booking] QR payment row creation failed', [
                'booking_id' => $this->booking->id,
                'error'      => $e->getMessage(),
            ]);
            $this->qrError = 'Could not record the payment locally. Try again.';
            return;
        }

        $this->qrPaymentIntentId = $qr['payment_intent_id'];
        $this->qrImage           = $qr['qr_image'];
        $this->qrExpiresAt       = $qr['expires_at'];
        $this->qrAmount          = $amount;
        $this->showQrModal       = true;

        $this->showPaymentModal = false;
        $this->qrError          = null;
    }

    public function checkQrPayment(): void
    {
        if (! $this->qrPaymentIntentId) {
            return;
        }

        $payMongo = app(PayMongoService::class);

        if (! $payMongo->finalizeQrPayment($this->qrPaymentIntentId)) {
            return;
        }

        // Service may have set RESERVED based on payment_type — re-derive
        // from scratch so a fully-paid booking lands on CONFIRMED.
        $this->refreshBookingState();
        $this->applyPaymentTransition();

        $this->closeQrModal();

        session()->flash('message', 'QR payment received and recorded.');
    }

    public function cancelQrPayment(): void
    {
        $this->closeQrModal();
    }

    protected function closeQrModal(): void
    {
        $this->showQrModal       = false;
        $this->qrImage           = null;
        $this->qrPaymentIntentId = null;
        $this->qrExpiresAt       = null;
        $this->qrAmount          = 0.0;
        $this->qrError           = null;
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

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
    public function dueLabel(): string
    {
        if (
            $this->booking->booking_type === Booking::TYPE_RESERVATION
            && $this->booking->status === Booking::STATUS_PENDING
        ) {
            return 'Reservation Fee Due';
        }

        return 'Balance Due';
    }

    /**
     * Rule J — never use asset() for storage. A relative /storage/...
     * path resolves on any origin.
     */
    #[Computed]
    public function tenantLogoUrl(): ?string
    {
        $path = $this->booking->tenant?->logo;

        return $path ? '/storage/' . ltrim($path, '/') : null;
    }
};
?>

@php
    $tenantAddress = trim(implode(', ', array_filter([
        $booking->tenant?->address,
        $booking->tenant?->barangay,
    ])));
    $tenantContact = trim(implode(' · ', array_filter([
        $booking->tenant?->contact_number,
        $booking->tenant?->email,
    ])));

    $canManagePayments = $this->tenantCan('manage payments');
    $canCollect        = $canManagePayments && $this->canCollectPayment();
    $dueNow            = $this->amountDueFor();
@endphp

<div x-data="{
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

    {{-- ═══ SCREEN LAYOUT ═══ --}}
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
                    #{{ $booking->booking_reference }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Back</span>
                </a>

                @if($canCollect)
                    <button type="button"
                            wire:click="openPaymentModal"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <span>Collect Payment</span>
                    </button>
                @endif

                <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate
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
            </div>
        </div>

        {{-- ═══ Flash messages ═══ --}}
        @if(session()->has('message'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4000)"
                 :class="show ? '' : 'hidden'"
                 role="status"
                 aria-live="polite"
                 class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('message') }}</span>
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
        @endif

        @if(session()->has('error'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5000)"
                 :class="show ? '' : 'hidden'"
                 role="alert"
                 aria-live="polite"
                 class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
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
        @endif

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
            <div class="h-1.5 {{ $this->statusMeta['stripe'] }}"></div>

            <div class="p-5 sm:p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-12 h-12 rounded-xl bg-primary-600 text-white flex items-center justify-center font-bold text-lg shrink-0">
                            {{ strtoupper(substr($booking->tenant?->name ?? 'B', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-400 dark:text-gray-500">
                                Booking Receipt
                            </p>
                            <p class="font-mono font-bold text-gray-900 dark:text-white mt-0.5 truncate">
                                #{{ $booking->booking_reference }}
                            </p>
                            @if($booking->tenant?->name)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    {{ $booking->tenant->name }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="shrink-0 sm:text-right">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-[11px] font-bold uppercase tracking-wider border-2
                                     {{ $this->statusMeta['stamp'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $this->statusMeta['dot'] }}"></span>
                            {{ $this->statusMeta['label'] }}
                        </span>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1.5 sm:text-right">
                            {{ $this->isReservation ? 'Reservation (20% fee)' : 'Full Payment' }}
                        </p>
                    </div>
                </div>

                @if($this->deadline)
                    <div x-data="{
                             deadline: {{ $this->deadline->timestamp * 1000 }},
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
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-5 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Guest</p>
                        @if($booking->user)
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $booking->user->name }}</p>
                            @if($booking->user->phone)
                                <a href="tel:{{ $booking->user->phone }}"
                                   class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors block truncate mt-0.5">
                                    {{ $booking->user->phone }}
                                </a>
                            @endif
                            @if($booking->user->email)
                                <a href="mailto:{{ $booking->user->email }}"
                                   class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors block truncate mt-0.5">
                                    {{ $booking->user->email }}
                                </a>
                            @endif
                        @else
                            <p class="text-sm text-gray-400 dark:text-gray-500 italic">Walk-in · no profile</p>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Start</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                            {{ $booking->check_in->format('M d, Y') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums mt-0.5">
                            {{ $booking->check_in->format('h:i A') }}
                        </p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">End</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                            {{ $booking->check_out->format('M d, Y') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums mt-0.5">
                            {{ $booking->check_out->format('h:i A') }} · {{ $this->days }} {{ Str::plural('day', $this->days) }}
                        </p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Total</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                            ₱{{ number_format((float) $booking->total_amount, 2) }}
                        </p>
                        @if($this->isSettled)
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                Paid in full
                            </p>
                        @else
                            <p class="text-xs text-rose-600 dark:text-rose-400 tabular-nums mt-1">
                                ₱{{ number_format($this->balance, 2) }} due
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

            <div class="space-y-6">

                @if($booking->items->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Activities
                            </h2>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            @foreach($booking->items as $item)
                                <div wire:key="item-{{ $item->id }}" class="flex justify-between items-start gap-4 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">
                                            {{ $item->property?->name ?? 'Unknown Activity' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                            ₱{{ number_format((float) $item->price, 2) }} × {{ $this->days }} {{ Str::plural('day', $this->days) }}
                                            @if((int) $item->quantity > 1)
                                                × {{ $item->quantity }}
                                            @endif
                                        </p>
                                    </div>
                                    <p class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums shrink-0 text-sm">
                                        ₱{{ number_format((float) $item->subtotal, 2) }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($booking->services->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Services
                            </h2>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            @foreach($booking->services as $service)
                                <div wire:key="service-{{ $service->id }}" class="flex justify-between items-start gap-4 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">
                                            {{ $service->service?->name ?? 'Unknown Service' }}
                                        </p>
                                        @if((int) $service->quantity > 1)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                Qty: {{ $service->quantity }}
                                            </p>
                                        @endif
                                    </div>
                                    <p class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums shrink-0 text-sm">
                                        ₱{{ number_format((float) $service->subtotal, 2) }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Payment History
                        </h2>
                    </div>

                    @if($booking->payments->isNotEmpty())
                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            @foreach($booking->payments as $payment)
                                <div wire:key="payment-{{ $payment->id }}" class="py-3 first:pt-0 last:pb-0 text-sm">
                                    <div class="flex justify-between items-start gap-4">
                                        <div class="min-w-0">
                                            <p class="text-gray-900 dark:text-white font-medium">
                                                <span class="capitalize">{{ str_replace('_', ' ', (string) $payment->payment_method) }}</span>
                                                <span class="text-gray-400 dark:text-gray-500 font-normal mx-1">·</span>
                                                <span class="text-gray-600 dark:text-gray-400 font-normal">
                                                    {{ $payment->payment_type === 'reservation' ? 'Reservation Fee' : 'Full Payment' }}
                                                </span>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                                {{ $payment->paid_at?->format('M d, Y · h:i A')
                                                    ?? $payment->created_at?->format('M d, Y · h:i A')
                                                    ?? '—' }}
                                            </p>
                                        </div>
                                        <p class="font-mono font-semibold tabular-nums shrink-0
                                                  {{ $payment->payment_status === 'paid' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                            ₱{{ number_format((float) $payment->amount, 2) }}
                                        </p>
                                    </div>

                                    @if($payment->reference_number || $payment->paymongo_session_id)
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mt-1 truncate">
                                            @if($payment->reference_number)
                                                Ref: {{ $payment->reference_number }}
                                            @endif
                                            @if($payment->reference_number && $payment->paymongo_session_id)
                                                <span class="mx-1">·</span>
                                            @endif
                                            @if($payment->paymongo_session_id)
                                                PayMongo: {{ $payment->paymongo_session_id }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">
                            No payments recorded yet.
                        </p>
                    @endif
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
                                ₱{{ number_format((float) $booking->total_amount, 2) }}
                            </dd>
                        </div>

                        @if($this->paidAmount > 0)
                            <div class="flex justify-between">
                                <dt class="text-emerald-600 dark:text-emerald-400">Amount Paid</dt>
                                <dd class="font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                                    −₱{{ number_format($this->paidAmount, 2) }}
                                </dd>
                            </div>
                        @endif

                        <div class="flex justify-between items-baseline pt-3 mt-1 border-t-2 border-dashed border-gray-200 dark:border-gray-700">
                            <dt class="text-sm font-bold uppercase tracking-wider {{ $this->isSettled ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                {{ $this->isSettled ? 'Balance' : 'Balance Due' }}
                            </dt>
                            <dd class="font-mono font-bold text-lg tabular-nums {{ $this->isSettled ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                ₱{{ number_format($this->balance, 2) }}
                            </dd>
                        </div>
                    </dl>

                    <div>
                        <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $this->isSettled ? 'bg-emerald-500' : 'bg-primary-600' }}"
                                 style="width: {{ (float) $booking->total_amount > 0
                                     ? min(100, ($this->paidAmount / (float) $booking->total_amount) * 100)
                                     : 0 }}%;"></div>
                        </div>
                        <div class="flex justify-between text-[11px] mt-1.5 text-gray-500 dark:text-gray-400">
                            <span class="tabular-nums">{{ number_format((float) $booking->total_amount > 0 ? ($this->paidAmount / (float) $booking->total_amount) * 100 : 0, 0) }}% paid</span>
                            <span class="tabular-nums">{{ $this->isSettled ? 'Settled' : '₱' . number_format($this->balance, 2) . ' remaining' }}</span>
                        </div>
                    </div>

                    @if($this->isReservation)
                        @php
                            $reservationFee   = round((float) $booking->total_amount * 0.20, 2);
                            $balanceOnArrival = max(0, (float) $booking->total_amount - $reservationFee);
                        @endphp
                        <div class="pt-4 border-t border-dashed border-gray-200 dark:border-gray-700 grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Reservation Fee
                                </p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">20% of total</p>
                                <p class="font-mono font-bold text-gray-900 dark:text-white mt-1 tabular-nums text-sm">
                                    ₱{{ number_format($reservationFee, 2) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Balance on Arrival
                                </p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">&nbsp;</p>
                                <p class="font-mono font-bold text-amber-600 dark:text-amber-400 mt-1 tabular-nums text-sm">
                                    ₱{{ number_format($balanceOnArrival, 2) }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                @if($canCollect)
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Collect Payment
                            </h2>
                        </div>

                        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ $this->dueLabel }}
                            </p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums mt-1">
                                ₱{{ number_format($dueNow, 2) }}
                            </p>
                            @if($booking->booking_type === 'reservation' && $booking->status === 'pending')
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                    20% reservation fee — booking becomes Reserved once paid.
                                </p>
                            @elseif($booking->booking_type === 'reservation' && $booking->status === 'reserved')
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                    Balance owed on arrival — booking becomes Confirmed once paid.
                                </p>
                            @endif
                        </div>

                        <button type="button"
                                wire:click="openPaymentModal"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <span>Collect Payment</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
    {{-- ═══ /SCREEN LAYOUT ═══ --}}


    {{-- ═══════════════════════════════════════════════════════════════
         PAYMENT METHOD MODAL
         ═══════════════════════════════════════════════════════════════ --}}
    @if($showPaymentModal)
        <div wire:key="payment-modal"
             wire:keydown.escape.window="closePaymentModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="payment-modal-title"
             class="no-print fixed inset-0 z-[90] overflow-y-auto">

            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="closePaymentModal"
                 aria-hidden="true"></div>

            <div class="relative flex min-h-full items-center justify-center p-2 sm:p-4">
                <div @click.stop
                     class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

                    <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="min-w-0">
                            <h2 id="payment-modal-title"
                                class="text-base font-semibold text-gray-900 dark:text-white">
                                Collect Payment
                            </h2>
                            <p class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                {{ $booking->booking_reference }}
                            </p>
                        </div>
                        <button type="button"
                                wire:click="closePaymentModal"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </header>

                    <div class="px-5 py-5 space-y-5">

                        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ $this->dueLabel }}
                            </p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums mt-1">
                                ₱{{ number_format($dueNow, 2) }}
                            </p>
                            @if($booking->booking_type === 'reservation' && $booking->status === 'pending')
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                    20% reservation fee — booking becomes Reserved once paid.
                                </p>
                            @elseif($booking->booking_type === 'reservation' && $booking->status === 'reserved')
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                    Balance owed on arrival — booking becomes Confirmed once paid.
                                </p>
                            @endif
                        </div>

                        @if($qrError)
                            <div class="rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 p-3 text-xs text-rose-700 dark:text-rose-300">
                                {{ $qrError }}
                            </div>
                        @endif

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                Payment Method
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer relative
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <input type="radio" wire:model.live="paymentMethod" value="cash" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer
                                                peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-7 h-7 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Cash</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[10px]">Recorded now</p>
                                    </div>
                                </label>

                                <label class="cursor-pointer relative
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <input type="radio" wire:model.live="paymentMethod" value="qr" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer
                                                peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-7 h-7 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="4" y="4" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.6"/>
                                            <rect x="14" y="4" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.6"/>
                                            <rect x="4" y="14" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.6"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M14 14h2v2h-2zM20 14v2M14 20h2M18 20h2v-2"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">QR Code</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[10px]">Scan via PayMongo</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <footer class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <button type="button"
                                wire:click="closePaymentModal"
                                class="inline-flex items-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Cancel
                        </button>

                        @if($paymentMethod === 'cash')
                            <button type="button"
                                    wire:click="submitCashPayment"
                                    wire:loading.attr="disabled"
                                    wire:target="submitCashPayment"
                                    class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="submitCashPayment">Record Cash</span>
                                <span wire:loading wire:target="submitCashPayment" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Processing…
                                </span>
                            </button>
                        @else
                            <button type="button"
                                    wire:click="submitQrPayment"
                                    wire:loading.attr="disabled"
                                    wire:target="submitQrPayment"
                                    class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="submitQrPayment">Generate QR</span>
                                <span wire:loading wire:target="submitQrPayment" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Processing…
                                </span>
                            </button>
                        @endif
                    </footer>
                </div>
            </div>
        </div>
    @endif


    {{-- ═══════════════════════════════════════════════════════════════
         QR MODAL
         ═══════════════════════════════════════════════════════════════ --}}
    @if($showQrModal && $qrImage)
        <div wire:key="qr-modal-active"
             wire:keydown.escape.window="cancelQrPayment"
             role="dialog"
             aria-modal="true"
             aria-labelledby="qr-modal-title"
             class="no-print fixed inset-0 z-[95] overflow-y-auto">

            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm"
                 wire:click="cancelQrPayment"
                 aria-hidden="true"></div>

            <div class="relative flex min-h-full items-center justify-center p-2 sm:p-4">
                <div @click.stop
                     class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

                    <header class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="min-w-0">
                            <h2 id="qr-modal-title"
                                class="text-base font-semibold text-gray-900 dark:text-white">
                                Scan to Pay
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Open any e-wallet or bank app to scan
                            </p>
                        </div>
                        <button type="button"
                                wire:click="cancelQrPayment"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </header>

                    <div class="px-5 py-5 space-y-4">
                        <div class="bg-white rounded-2xl p-4 flex items-center justify-center border-2 border-gray-200 dark:border-gray-700">
                            <img src="{{ $qrImage }}" alt="PayMongo QR Code" class="w-64 h-64 object-contain">
                        </div>

                        <div class="text-center">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount due</p>
                            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1 tabular-nums">
                                ₱{{ number_format($qrAmount, 2) }}
                            </p>
                            @if($qrExpiresAt)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Expires {{ \Carbon\Carbon::parse($qrExpiresAt)->diffForHumans() }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs text-blue-800 dark:text-blue-300 font-medium">
                                Waiting for payment confirmation…
                            </p>
                        </div>
                    </div>

                    <footer class="flex items-center gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <button type="button"
                                wire:click="checkQrPayment"
                                wire:loading.attr="disabled"
                                wire:target="checkQrPayment"
                                class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="checkQrPayment">Check Now</span>
                            <span wire:loading wire:target="checkQrPayment" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Checking…
                            </span>
                        </button>
                        <button type="button"
                                wire:click="cancelQrPayment"
                                class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Close
                        </button>
                    </footer>
                </div>
            </div>
        </div>
    @endif


    {{-- ═══════════════════════════════════════════════════════════════
         PRINT-ONLY RECEIPT — 80mm thermal receipt, centered on paper
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="receipt-print-only">
        <div class="receipt-document">

            {{-- ─── Letterhead ─────────────────────────────────── --}}
            <header class="receipt-letterhead">
                @if($this->tenantLogoUrl)
                    <img src="{{ $this->tenantLogoUrl }}"
                         alt="{{ $booking->tenant->name }}"
                         class="receipt-logo">
                @endif

                <h1 class="receipt-business-name">
                    {{ $booking->tenant?->name ?? config('app.name') }}
                </h1>

                @if($tenantAddress !== '')
                    <p class="receipt-letterhead-meta">{{ $tenantAddress }}</p>
                @endif

                @if($tenantContact !== '')
                    <p class="receipt-letterhead-meta">{{ $tenantContact }}</p>
                @endif

                <p class="receipt-document-title">Booking Receipt</p>
            </header>

            <div class="receipt-rule-dashed"></div>

            {{-- ─── Reference / Status / Issued / Type ─────────── --}}
            <div class="receipt-row">
                <span class="receipt-row-label">Reference</span>
                <span class="receipt-row-value receipt-mono">#{{ $booking->booking_reference }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Status</span>
                <span class="receipt-row-value">{{ $this->statusMeta['label'] }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Issued</span>
                <span class="receipt-row-value">{{ now()->format('M j, Y · g:i A') }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Type</span>
                <span class="receipt-row-value">{{ $this->isReservation ? 'Reservation (20% fee)' : 'Full Payment' }}</span>
            </div>

            <div class="receipt-rule-dashed"></div>

            {{-- ─── Guest ──────────────────────────────────────── --}}
            <h2 class="receipt-section-title">Guest</h2>
            @if($booking->user)
                <p class="receipt-line-bold">{{ $booking->user->name }}</p>
                @if($booking->user->phone)
                    <p class="receipt-line">{{ $booking->user->phone }}</p>
                @endif
                @if($booking->user->email)
                    <p class="receipt-line">{{ $booking->user->email }}</p>
                @endif
            @else
                <p class="receipt-line-italic">Walk-in guest</p>
            @endif

            <div class="receipt-rule-dashed"></div>

            {{-- ─── Booking Dates ──────────────────────────────── --}}
            <h2 class="receipt-section-title">Booking Dates</h2>
            <div class="receipt-row">
                <span class="receipt-row-label">Start</span>
                <span class="receipt-row-value">{{ $booking->check_in->format('M j, Y · g:i A') }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">End</span>
                <span class="receipt-row-value">{{ $booking->check_out->format('M j, Y · g:i A') }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Duration</span>
                <span class="receipt-row-value">{{ $this->days }} {{ Str::plural('day', $this->days) }}</span>
            </div>

            <div class="receipt-rule-dashed"></div>

            {{-- ─── Activities & Services ──────────────────────── --}}
            <h2 class="receipt-section-title">Activities &amp; Services</h2>

            @if($booking->items->isEmpty() && $booking->services->isEmpty())
                <p class="receipt-line-italic">No items recorded.</p>
            @else
                <table class="receipt-table">
                    <tbody>
                        @foreach($booking->items as $item)
                            <tr wire:key="print-item-{{ $item->id }}">
                                <td>
                                    <p class="receipt-item-name">{{ $item->property?->name ?? 'Unknown Activity' }}</p>
                                    <p class="receipt-item-detail">
                                        ₱{{ number_format((float) $item->price, 2) }} × {{ $this->days }} {{ Str::plural('day', $this->days) }}
                                        @if((int) $item->quantity > 1)
                                            × {{ $item->quantity }}
                                        @endif
                                    </p>
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱{{ number_format((float) $item->subtotal, 2) }}
                                </td>
                            </tr>
                        @endforeach
                        @foreach($booking->services as $service)
                            <tr wire:key="print-service-{{ $service->id }}">
                                <td>
                                    <p class="receipt-item-name">{{ $service->service?->name ?? 'Unknown Service' }}</p>
                                    @if((int) $service->quantity > 1)
                                        <p class="receipt-item-detail">Qty: {{ $service->quantity }}</p>
                                    @endif
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱{{ number_format((float) $service->subtotal, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="receipt-rule"></div>

            {{-- ─── Totals ─────────────────────────────────────── --}}
            <div class="receipt-row">
                <span class="receipt-row-label">Total</span>
                <span class="receipt-row-value receipt-mono">₱{{ number_format((float) $booking->total_amount, 2) }}</span>
            </div>

            @if($this->paidAmount > 0)
                <div class="receipt-row">
                    <span class="receipt-row-label">Amount Paid</span>
                    <span class="receipt-row-value receipt-mono">−₱{{ number_format($this->paidAmount, 2) }}</span>
                </div>
            @endif

            <div class="receipt-row receipt-row-total">
                <span class="receipt-row-label">{{ $this->isSettled ? 'Balance' : 'Balance Due' }}</span>
                <span class="receipt-row-value receipt-mono">₱{{ number_format($this->balance, 2) }}</span>
            </div>

            @if($this->isReservation)
                @php
                    $reservationFee   = round((float) $booking->total_amount * 0.20, 2);
                    $balanceOnArrival = max(0, (float) $booking->total_amount - $reservationFee);
                @endphp
                <div class="receipt-rule-dashed"></div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Reservation Fee (20%)</span>
                    <span class="receipt-row-value receipt-mono">₱{{ number_format($reservationFee, 2) }}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Balance on Arrival</span>
                    <span class="receipt-row-value receipt-mono">₱{{ number_format($balanceOnArrival, 2) }}</span>
                </div>
            @endif

            {{-- ─── Payment History ─────────────────────────────── --}}
            @if($booking->payments->isNotEmpty())
                <div class="receipt-rule-dashed"></div>

                <h2 class="receipt-section-title">Payment History</h2>

                @foreach($booking->payments as $payment)
                    <div class="receipt-payment" wire:key="print-payment-{{ $payment->id }}">
                        <div class="receipt-row">
                            <span class="receipt-row-label capitalize">
                                {{ str_replace('_', ' ', (string) $payment->payment_method) }}
                                @if($payment->payment_type === 'reservation')
                                    · Fee
                                @endif
                            </span>
                            <span class="receipt-row-value receipt-mono">₱{{ number_format((float) $payment->amount, 2) }}</span>
                        </div>
                        <p class="receipt-item-detail">
                            {{ $payment->paid_at?->format('M j, Y · g:i A')
                                ?? $payment->created_at?->format('M j, Y · g:i A')
                                ?? '—' }}
                            @if($payment->reference_number)
                                · Ref: {{ $payment->reference_number }}
                            @endif
                        </p>
                    </div>
                @endforeach
            @endif

            <div class="receipt-rule-thick"></div>

            {{-- ─── Footer ─────────────────────────────────────── --}}
            <footer class="receipt-footer">
                <p class="receipt-footer-primary">Thank you for booking with us!</p>
                <p class="receipt-footer-meta">Generated {{ now()->format('M j, Y · g:i A') }}</p>
                <p class="receipt-footer-ref receipt-mono">#{{ $booking->booking_reference }}</p>
            </footer>
        </div>
    </div>
    {{-- ═══ /PRINT-ONLY RECEIPT ═══ --}}


    {{-- ═══════════════════════════════════════════════════════════════
         INLINE PRINT CSS — 80mm thermal receipt, centered
         ═══════════════════════════════════════════════════════════════ --}}
    <style>
        /* Screen: keep the receipt hidden. */
        .receipt-print-only { display: none; }

        @media print {
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

            body * { visibility: hidden !important; }
            .receipt-print-only,
            .receipt-print-only * { visibility: visible !important; }

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

        .receipt-document {
            width: 100%;
            color: #111;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }

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

        .receipt-rule        { border-top: 1px solid #111; margin: 2.5mm 0; }
        .receipt-rule-dashed { border-top: 1px dashed #aaa; margin: 2.5mm 0; }
        .receipt-rule-thick  { border-top: 1px solid #111; margin: 3mm 0; }

        .receipt-section-title {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #666;
            margin: 0 0 1.5mm;
            page-break-after: avoid;
        }

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

        .receipt-payment {
            padding: 0.8mm 0;
            page-break-inside: avoid;
        }

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

        .receipt-mono {
            font-family: ui-monospace, 'SF Mono', 'Cascadia Mono', Menlo, Consolas, monospace;
            font-variant-numeric: tabular-nums;
        }

        .receipt-table .capitalize,
        .receipt-payment .capitalize { text-transform: capitalize; }
    </style>


    {{-- ═══════════════════════════════════════════════════════════════
         PRINT SCOPE HANDLER
         ═══════════════════════════════════════════════════════════════ --}}
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

</div>