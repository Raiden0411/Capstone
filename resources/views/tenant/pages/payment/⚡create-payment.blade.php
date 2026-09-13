{{-- resources/views/tenant/pages/payment/⚡create-payment.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new
#[Layout('tenant.layouts.app')]
#[Title('Record Payment')]
class extends Component
{
    /** Bound from route. Auto-locked (Eloquent model). */
    public Booking $booking;

    public float $amount = 0;

    public string $payment_method = 'cash';

    public string $reference_number = '';

    public string $payment_type = Payment::TYPE_FULL;

    /** Remaining balance at the moment of mount — used for display + validation. */
    #[Locked]
    public float $remainingBalance = 0;

    /** Total paid at the moment of mount — used for display. */
    #[Locked]
    public float $alreadyPaid = 0;

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount($booking): void
    {
        if (!$booking instanceof Booking) {
            $booking = Booking::withoutGlobalScope(TenantScope::class)
                ->with(['user:id,name,email,phone'])
                ->findOrFail((int) $booking);
        }

        abort_unless($booking->tenant_id === Auth::user()->tenant_id, 403, 'Unauthorized.');

        $this->booking = $booking;

        // Snapshot for display. Live totals are always recomputed at save-time.
        $this->alreadyPaid      = (float) $booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        $this->remainingBalance = max(0, (float) $booking->total_amount - $this->alreadyPaid);

        if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
            session()->flash('error', 'Cannot record payment on a ' . $booking->status . ' booking.');
            $this->redirectRoute('tenant.bookings.show', $booking->id, navigate: true);
            return;
        }

        // Default the payment type to match the booking intent, then let
        // recalculateDefaultAmount() set the correct default amount.
        $this->payment_type = $booking->booking_type === Booking::TYPE_RESERVATION
            ? Payment::TYPE_RESERVATION
            : Payment::TYPE_FULL;

        $this->recalculateDefaultAmount();
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method'   => ['required', 'in:cash,gcash,paymaya,card'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'payment_type'     => ['required', 'in:full,reservation'],
        ];
    }

    public function updated(string $field): void
    {
        if ($field === 'reference_number') {
            $this->reference_number = trim((string) $this->reference_number);
        }

        if ($field === 'payment_type') {
            $this->recalculateDefaultAmount();
        }
    }

    /**
     * Set the amount input to the canonical value for the current payment type:
     *   - reservation  → 20% of the booking total
     *   - full         → the remaining balance
     */
    protected function recalculateDefaultAmount(): void
    {
        $total = (float) $this->booking->total_amount;

        if ($this->payment_type === Payment::TYPE_RESERVATION) {
            $this->amount = round($total * 0.20, 2);
            return;
        }

        $paid = (float) $this->booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        $this->amount = round(max(0, $total - $paid), 2);
    }

    // ─────────────────────────────────────────────────────────
    //  Cash payment
    // ─────────────────────────────────────────────────────────

    public function processCashPayment()
    {
        $this->validate();

        try {
            DB::transaction(function (): void {
                // Re-fetch and lock the booking, scoped to the caller's tenant.
                $booking = Booking::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->whereKey($this->booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $totalPaid = (float) $booking->payments()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('payment_status', 'paid')
                    ->sum('amount');

                $balance = (float) $booking->total_amount - $totalPaid;

                if ($this->amount > $balance + 0.0001) {
                    throw new \DomainException(
                        'Payment amount (₱' . number_format($this->amount, 2) . ') exceeds the remaining balance (₱' . number_format($balance, 2) . ').'
                    );
                }

                Payment::create([
                    'tenant_id'        => Auth::user()->tenant_id,
                    'booking_id'       => $booking->id,
                    'amount'           => $this->amount,
                    'payment_method'   => $this->payment_method,
                    'payment_status'   => 'paid',
                    'payment_type'     => $this->payment_type,
                    'reference_number' => $this->reference_number ?: null,
                    'paid_at'          => now(),
                ]);

                $this->updateBookingStatus($booking);
            });
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            Log::error('Cash payment recording failed', [
                'tenant_id'  => Auth::user()->tenant_id,
                'booking_id' => $this->booking->id,
                'error'      => $e->getMessage(),
            ]);
            session()->flash('error', 'Could not record the payment. Please try again.');
            return null;
        }

        session()->flash('message', 'Payment recorded successfully.');
        $this->dispatch('payment-recorded');
        return $this->redirectRoute('tenant.bookings.show', $this->booking->id, navigate: true);
    }

    // ─────────────────────────────────────────────────────────
    //  Online payment (PayMongo checkout)
    // ─────────────────────────────────────────────────────────

    public function processOnlinePayment(PayMongoService $payMongo)
    {
        $this->validate([
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', 'in:gcash,paymaya,card'],
            'payment_type'   => ['required', 'in:full,reservation'],
        ]);

        // Locked re-check: still-pending, still-owned, amount within balance.
        $booking = null;
        try {
            $booking = DB::transaction(function () {
                $locked = Booking::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->whereKey($this->booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($locked->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
                    throw new \DomainException('This booking cannot accept new payments.');
                }

                $totalPaid = (float) $locked->payments()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('payment_status', 'paid')
                    ->sum('amount');

                $balance = (float) $locked->total_amount - $totalPaid;

                if ($this->amount > $balance + 0.0001) {
                    throw new \DomainException(
                        'Payment amount (₱' . number_format($this->amount, 2) . ') exceeds the remaining balance (₱' . number_format($balance, 2) . ').'
                    );
                }

                return $locked;
            });
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            Log::error('Online payment preflight failed', [
                'tenant_id'  => Auth::user()->tenant_id,
                'booking_id' => $this->booking->id,
                'error'      => $e->getMessage(),
            ]);
            session()->flash('error', 'Could not verify the booking. Please try again.');
            return null;
        }

        $user = $booking->user;

        $session = $payMongo->createCheckoutSession([
            'customer_name'        => $user?->name ?? 'Guest',
            'customer_email'       => $user?->email ?? 'guest@example.com',
            'customer_phone'       => $user?->phone,
            'amount'               => $this->amount,
            'description'          => "Booking #{$booking->booking_reference}",
            'item_name'            => $this->payment_type === Payment::TYPE_RESERVATION
                ? 'Reservation Fee'
                : 'Activity Payment',
            'success_url'          => route('tenant.payments.success', ['booking' => $booking->id]),
            'cancel_url'           => route('tenant.payments.cancel',  ['booking' => $booking->id]),
            'metadata'             => [
                'booking_id' => $booking->id,
                'tenant_id'  => Auth::user()->tenant_id,
            ],
            'payment_method_types' => ['gcash', 'paymaya', 'card', 'qrph'],
        ]);

        if (!$session) {
            session()->flash('error', 'Unable to initiate payment. Please try again.');
            return null;
        }

        try {
            Payment::create([
                'tenant_id'           => Auth::user()->tenant_id,
                'booking_id'          => $booking->id,
                'amount'              => $this->amount,
                'payment_method'      => $this->payment_method,
                'payment_status'      => 'pending',
                'payment_type'        => $this->payment_type,
                'paymongo_session_id' => $session['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Online payment row creation failed', [
                'tenant_id'  => Auth::user()->tenant_id,
                'booking_id' => $booking->id,
                'session_id' => $session['id'],
                'error'      => $e->getMessage(),
            ]);
            session()->flash('error', 'Payment session was created but could not be recorded locally. Contact support.');
            return null;
        }

        return redirect()->away($session['checkout_url']);
    }

    // ─────────────────────────────────────────────────────────
    //  Booking status transition
    // ─────────────────────────────────────────────────────────

    /**
     * Transition the booking based on what's just been paid.
     *
     * Rules (must be called inside the payment transaction):
     *   - reservation payment     → STATUS_RESERVED  (from pending/reserved)
     *   - full payment covering the total → STATUS_CONFIRMED (from pending/reserved)
     *   - otherwise               → no change
     *
     * Only transitions from {pending, reserved} are allowed — never touch
     * an already checked-in, completed, or cancelled booking.
     */
    protected function updateBookingStatus(Booking $booking): void
    {
        if (!in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_RESERVED], true)) {
            return;
        }

        $totalPaid = (float) $booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        if ($this->payment_type === Payment::TYPE_RESERVATION) {
            $booking->update(['status' => Booking::STATUS_RESERVED]);
            return;
        }

        if ((float) $booking->total_amount > 0
            && $totalPaid >= (float) $booking->total_amount) {
            $booking->update(['status' => Booking::STATUS_CONFIRMED]);
        }
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

    {{-- Header — tenant eyebrow pattern --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Payments
            </p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Record Payment
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Booking <span class="font-mono font-semibold">{{ $booking->booking_reference }}</span>
                · {{ $booking->user->name ?? 'Walk-in Guest' }}
            </p>
        </div>
        <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Booking
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session()->has('error'))
        <div class="flex items-start gap-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-red-700 dark:text-red-300 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-red-700 dark:text-red-300">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">

        {{-- Booking summary --}}
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Amount</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white mt-1 tabular-nums">
                    ₱{{ number_format((float) $booking->total_amount, 2) }}
                </p>
            </div>
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Already Paid</p>
                <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">
                    ₱{{ number_format($alreadyPaid, 2) }}
                </p>
            </div>
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Remaining Balance</p>
                <p class="text-lg font-bold mt-1 tabular-nums {{ $remainingBalance > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    ₱{{ number_format($remainingBalance, 2) }}
                </p>
            </div>
        </div>

        <form wire:submit="{{ $payment_method === 'cash' ? 'processCashPayment' : 'processOnlinePayment' }}"
              class="space-y-5"
              x-data="{ saved: false }"
              @payment-recorded.window="saved = true; setTimeout(() => saved = false, 2200)">

            {{-- Payment type --}}
            <div>
                <label for="payment-type" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                    Payment Type <span class="text-red-500">*</span>
                </label>
                <select id="payment-type" wire:model.live="payment_type" class="input w-full">
                    <option value="full">Full Payment</option>
                    <option value="reservation">Reservation Fee (20%)</option>
                </select>
                @error('payment_type') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Amount --}}
            <div>
                <label for="payment-amount" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                    Amount to Pay <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm font-semibold">₱</span>
                    <input id="payment-amount" type="number" step="0.01" min="0.01"
                           wire:model="amount"
                           class="input w-full pl-9 font-mono tabular-nums">
                </div>
                @error('amount') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                @if($payment_type === 'full' && $amount >= $remainingBalance && $remainingBalance > 0)
                    <p class="mt-1.5 text-xs text-primary-600 dark:text-primary-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Full payment — the booking will be confirmed automatically.
                    </p>
                @elseif($payment_type === 'reservation' && $amount > 0)
                    <p class="mt-1.5 text-xs text-blue-600 dark:text-blue-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Reservation fee — the booking will be marked as <strong>reserved</strong>.
                    </p>
                @endif
            </div>

            {{-- Payment method --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">
                    Payment Method <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach([
                        ['cash',    'Cash',    '<svg class="w-7 h-7 mx-auto text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
                        ['gcash',   'GCash',   '<svg class="w-7 h-7 mx-auto text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'],
                        ['paymaya', 'Maya',    '<svg class="w-7 h-7 mx-auto text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>'],
                        ['card',    'Card',    '<svg class="w-7 h-7 mx-auto text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>'],
                    ] as [$val, $label, $icon])
                        <label class="cursor-pointer" wire:key="payment-method-{{ $val }}">
                            <input type="radio" wire:model.live="payment_method" value="{{ $val }}" class="sr-only peer">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 text-center transition-all duration-200
                                        peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-500/10
                                        peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50
                                        hover:border-gray-300 dark:hover:border-gray-600 active:scale-[0.98]">
                                {!! $icon !!}
                                <p class="text-gray-900 dark:text-white font-semibold text-xs mt-1">{{ $label }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('payment_method') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Reference (cash only) --}}
            @if($payment_method === 'cash')
                <div>
                    <label for="payment-ref" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                        Reference Number (Optional)
                    </label>
                    <input id="payment-ref" type="text" wire:model="reference_number"
                           placeholder="e.g. OR number, receipt #"
                           class="input w-full">
                    @error('reference_number') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            @else
                <div class="flex items-start gap-3 p-4 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        You will be redirected to PayMongo to complete the payment. The booking status updates automatically once the payment succeeds.
                    </p>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="processCashPayment,processOnlinePayment"
                        class="btn-primary w-full sm:w-auto active:scale-95 transition-transform
                               inline-flex items-center justify-center gap-2
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="processCashPayment,processOnlinePayment">
                        {{ $payment_method === 'cash' ? 'Record Payment' : 'Proceed to Pay' }}
                    </span>
                    <span wire:loading wire:target="processCashPayment,processOnlinePayment" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Processing…
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                   class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform
                          inline-flex items-center justify-center gap-2
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Cancel
                </a>
                <span x-show="saved" x-transition class="sm:ml-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-primary-50 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Done!
                </span>
            </div>
        </form>
    </div>
</div>