{{-- resources/views/public/pages/⚡payment-processing.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\TenantScope;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new
#[Layout('layouts.app')]
#[Title('Processing Payment')]
class extends Component
{
    private const PAYMONGO_API = 'https://api.paymongo.com/v1';
    private const POLL_GATEWAY_GAP = 8;
    private const THROTTLE_KEY = 'paymongo_processing_throttle';

    #[Locked]
    public int $bookingId;

    public ?Booking $booking = null;

    #[Locked]
    public bool $deadlinePassed = false;

    #[Locked]
    public int $deadlineMinutes = Booking::PAYMENT_DEADLINE_MINUTES;

    #[Locked]
    public ?int $secondsRemaining = null;

    #[Locked]
    public ?string $lastGatewayStatus = null;

    #[Locked]
    public ?string $lastCheckedReference = null;

    #[Locked]
    public ?int $lastPaymentCount = null;

    /**
     * On mount, we do THREE things in order, each of which can short-
     * circuit the rest:
     *
     *   1. Redirect immediately if the booking is already finalised
     *      (confirmed / reserved) or cancelled — no point rendering a
     *      waiting UI for a booking that has already resolved. This
     *      catches the case where the PayMongo webhook landed BEFORE
     *      the browser redirect did.
     *
     *   2. Run a synchronous PayMongo lookup to catch the case where
     *      the payment succeeded but the webhook hasn't landed yet.
     *      Without this, the very first render shows the countdown
     *      for a payment the user has already made — the bug this
     *      fix addresses.
     *
     *   3. Only if the booking is still genuinely pending after both
     *      of the above, compute the countdown state. That's the only
     *      time the timer is legitimate: the user still has time to
     *      complete payment.
     */
    public function mount($bookingId): void
    {
        $this->bookingId = (int) $bookingId;

        $this->booking = Booking::withoutGlobalScope(TenantScope::class)
            ->findOrFail($this->bookingId);

        abort_unless(Auth::id() === $this->booking->user_id, 403);

        // ── 1. Terminal-state short-circuit ──
        if ($this->isFinalised()) {
            session()->flash('message', 'Payment successful! Your booking is confirmed.');
            $this->redirect(route('my-bookings'), navigate: true);
            return;
        }

        if ($this->booking->status === Booking::STATUS_CANCELLED) {
            session()->flash('error', 'This booking was cancelled because payment was not completed in time.');
            $this->redirect(route('my-bookings'), navigate: true);
            return;
        }

        // ── 2. One synchronous gateway check ──
        // Bypasses the throttle deliberately: this is the user's first
        // look at the page after returning from PayMongo. If the
        // payment succeeded, we want to confirm and redirect before
        // rendering — not wait 5–10 seconds for the first wire:poll.
        $this->forceCheck(app(PayMongoService::class));

        // forceCheck may have redirected. If it did, don't render.
        if ($this->isFinalised() || $this->booking->status === Booking::STATUS_CANCELLED) {
            return;
        }

        // ── 3. Booking is still genuinely pending ──
        // Compute the countdown. forceCheck has already set
        // $deadlinePassed and $secondsRemaining via checkStatus(), but
        // we recompute defensively in case the early returns above
        // skipped those assignments.
        $this->deadlinePassed   = $this->computeDeadlinePassed();
        $this->secondsRemaining = $this->computeSecondsRemaining();
    }

    public function hydrate(): void
    {
        $this->refreshBooking();

        abort_unless(Auth::id() === $this->booking->user_id, 403);

        $this->deadlinePassed   = $this->computeDeadlinePassed();
        $this->secondsRemaining = $this->computeSecondsRemaining();
    }

    public function checkStatus(PayMongoService $payMongo)
    {
        $this->refreshBooking();

        if ($this->isFinalised()) {
            session()->flash('message', 'Payment successful! Your booking is confirmed.');
            return $this->redirectToBookings();
        }

        if ($this->booking->status === Booking::STATUS_CANCELLED) {
            session()->flash('error', 'This booking was cancelled because payment was not completed in time.');
            return $this->redirectToBookings();
        }

        if ($this->computeDeadlinePassed()) {
            $this->deadlinePassed   = true;
            $this->secondsRemaining = 0;
            return null;
        }

        $this->secondsRemaining = $this->computeSecondsRemaining();

        $payment = $payMongo->findPaymentForBooking($this->booking->id)
            ?? Payment::withoutGlobalScope(TenantScope::class)
                ->where('booking_id', $this->booking->id)
                ->latest('id')
                ->first();

        if (!$payment) {
            return null;
        }

        if ($payment->payment_status === 'paid') {
            session()->flash('message', 'Payment received. Confirming your booking…');
            return $this->redirectToBookings();
        }

        $reference = $payment->paymongo_session_id ?: $payment->reference_number;
        if (!$reference) {
            return null;
        }

        if ($this->shouldPingGateway()) {
            $this->lastCheckedReference = $reference;

            $snapshot = $this->queryPayMongoCheckoutSnapshot($reference);

            $this->lastGatewayStatus = $snapshot['is_paid']
                ? 'paid'
                : ($snapshot['status'] ?? '—');
            $this->lastPaymentCount  = $snapshot['payment_count'];

            if ($snapshot['is_paid']) {
                $payMongo->finalizeCheckoutSession($reference);
            }
        }

        $this->refreshBooking();

        if ($this->isFinalised()) {
            session()->flash('message', 'Payment successful! Your booking is confirmed.');
            return $this->redirectToBookings();
        }

        return null;
    }

    public function forceCheck(PayMongoService $payMongo)
    {
        Cache::forget($this->throttleKey());
        $this->lastGatewayStatus    = null;
        $this->lastCheckedReference = null;
        $this->lastPaymentCount     = null;
        return $this->checkStatus($payMongo);
    }

    // ────────────────────────────────────────────────────────────────
    //  PayMongo REST — reads BOTH status and payments[]
    // ────────────────────────────────────────────────────────────────

    /**
     * @return array{is_paid: bool, status: ?string, payment_count: int, payment_statuses: array}
     */
    protected function queryPayMongoCheckoutSnapshot(string $sessionId): array
    {
        $empty = [
            'is_paid'          => false,
            'status'           => null,
            'payment_count'    => 0,
            'payment_statuses' => [],
        ];

        $secret = (string) config('paymongo.secret_key');
        if ($secret === '') {
            Log::error('[processing] PAYMONGO_SECRET_KEY is not configured.');
            return $empty;
        }

        try {
            $response = Http::withBasicAuth($secret, '')
                ->acceptJson()
                ->timeout(10)
                ->get(self::PAYMONGO_API . "/checkout_sessions/{$sessionId}");

            if ($response->failed()) {
                Log::warning('[processing] PayMongo REST lookup failed', [
                    'session_id' => $sessionId,
                    'http'       => $response->status(),
                    'body'       => $response->json() ?? $response->body(),
                ]);
                return $empty;
            }

            $attrs    = (array) ($response->json('data.attributes') ?? []);
            $status   = (string) ($attrs['status'] ?? '');
            $payments = is_array($attrs['payments'] ?? null) ? $attrs['payments'] : [];

            $paymentStatuses = array_values(array_filter(array_map(
                static fn ($p) => $p['attributes']['status'] ?? $p['status'] ?? null,
                $payments
            ), static fn ($s) => is_string($s) && $s !== ''));

            $isPaid = in_array($status, ['paid', 'succeeded'], true);

            if (!$isPaid) {
                foreach ($paymentStatuses as $ps) {
                    if (in_array($ps, ['paid', 'succeeded'], true)) {
                        $isPaid = true;
                        break;
                    }
                }
            }

            Log::info('[processing] PayMongo REST lookup', [
                'session_id'       => $sessionId,
                'status'           => $status,
                'payment_count'    => count($payments),
                'payment_statuses' => $paymentStatuses,
                'is_paid'          => $isPaid,
            ]);

            return [
                'is_paid'          => $isPaid,
                'status'           => $status !== '' ? $status : null,
                'payment_count'    => count($payments),
                'payment_statuses' => $paymentStatuses,
            ];
        } catch (\Throwable $e) {
            Log::error('[processing] PayMongo REST lookup threw', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return $empty;
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Internal helpers
    // ────────────────────────────────────────────────────────────────

    protected function refreshBooking(): void
    {
        $this->booking = Booking::withoutGlobalScope(TenantScope::class)
            ->find($this->bookingId);

        abort_if(!$this->booking, 404);
    }

    protected function isFinalised(): bool
    {
        return in_array($this->booking->status, [
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_RESERVED,
        ], true);
    }

    protected function computeDeadlinePassed(): bool
    {
        $created = $this->booking->created_at;
        if (!$created) {
            return false;
        }

        return $created->copy()
            ->addMinutes($this->deadlineMinutes)
            ->isPast();
    }

    protected function computeSecondsRemaining(): ?int
    {
        $created = $this->booking?->created_at;
        if (!$created) {
            return null;
        }

        $deadline = $created->copy()->addMinutes($this->deadlineMinutes);

        if ($deadline->isPast()) {
            return 0;
        }

        return (int) now()->diffInSeconds($deadline);
    }

    protected function shouldPingGateway(): bool
    {
        $key  = $this->throttleKey();
        $last = (int) Cache::get($key, 0);
        $now  = time();

        if (($now - $last) < self::POLL_GATEWAY_GAP) {
            return false;
        }

        Cache::put($key, $now, self::POLL_GATEWAY_GAP * 3);

        return true;
    }

    protected function throttleKey(): string
    {
        return self::THROTTLE_KEY . ':' . $this->booking->id;
    }

    protected function redirectToBookings()
    {
        return $this->redirect(route('my-bookings'), navigate: true);
    }
};
?>

@push('styles')
    @once
        <style>
            .processing-ambient {
                background:
                    radial-gradient(ellipse 70% 55% at 50% 0%, rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 90% 100%, rgba(59,130,246,.06) 0%, transparent 55%);
            }
            .dark .processing-ambient {
                background:
                    radial-gradient(ellipse 70% 55% at 50% 0%, rgba(245,158,11,.10) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 90% 100%, rgba(59,130,246,.08) 0%, transparent 55%);
            }

            @keyframes processingSweep {
                0%   { transform: translateX(-100%); opacity: 0; }
                20%  { opacity: 1; }
                80%  { opacity: 1; }
                100% { transform: translateX(400%); opacity: 0; }
            }
            .processing-sweep {
                animation: processingSweep 1.8s ease-in-out infinite;
            }

            @keyframes ringRotate {
                from { transform: rotate(0deg); }
                to   { transform: rotate(360deg); }
            }
            .processing-ring {
                animation: ringRotate 3.2s linear infinite;
            }

            @media (prefers-reduced-motion: reduce) {
                .processing-sweep, .processing-ring { animation: none; }
            }
        </style>
    @endonce
@endpush

<div class="relative min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8"
     @if(!$deadlinePassed) wire:poll.5s="checkStatus" @endif>

    <div class="processing-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="w-full max-w-md">

        <div role="status"
             aria-live="polite"
             class="relative bg-white dark:bg-gray-900
                    border border-gray-200 dark:border-gray-800
                    rounded-3xl shadow-2xl shadow-gray-900/10 dark:shadow-black/40
                    overflow-hidden">

            @if(!$deadlinePassed)
                <div class="absolute top-0 left-0 right-0 h-1 overflow-hidden" aria-hidden="true">
                    <div class="processing-sweep h-full w-1/3
                                bg-linear-to-r from-transparent via-primary-500 to-transparent
                                motion-reduce:hidden"></div>
                </div>
            @endif

            <div class="p-6 sm:p-8">

                @if($deadlinePassed)

                    <div class="text-center">
                        <div class="relative mx-auto w-20 h-20">
                            <div class="absolute inset-0 rounded-full
                                        bg-linear-to-br from-amber-100 to-amber-50
                                        dark:from-amber-500/20 dark:to-amber-500/5"></div>
                            <div class="absolute inset-2 rounded-full
                                        bg-white dark:bg-gray-900
                                        flex items-center justify-center
                                        ring-1 ring-amber-200/80 dark:ring-amber-500/30">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="w-8 h-8 text-amber-600 dark:text-amber-400"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>

                        <h1 class="mt-6 font-display text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white
                                   [text-wrap:balance]">
                            Payment window has closed
                        </h1>

                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed [text-wrap:pretty]">
                            We haven't received a payment confirmation within the
                            {{ $deadlineMinutes }}-minute window. The booking will be
                            released shortly — no charge has been made.
                        </p>
                    </div>

                @else

                    <div class="text-center">

                        <div class="relative mx-auto w-24 h-24">
                            <div class="absolute inset-0 rounded-full
                                        bg-primary-500/10 dark:bg-primary-500/15
                                        blur-xl" aria-hidden="true"></div>

                            <svg class="processing-ring absolute inset-0 w-full h-full motion-reduce:hidden"
                                 viewBox="0 0 96 96"
                                 fill="none" aria-hidden="true">
                                <circle cx="48" cy="48" r="44"
                                        stroke="currentColor"
                                        class="text-primary-500/30 dark:text-primary-400/30"
                                        stroke-width="1.5"
                                        stroke-dasharray="4 8"
                                        stroke-linecap="round"/>
                            </svg>

                            <div class="absolute inset-3 rounded-full
                                        bg-white dark:bg-gray-900
                                        border border-primary-100 dark:border-primary-500/20
                                        flex items-center justify-center
                                        shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="w-8 h-8 text-primary-600 dark:text-primary-400
                                            animate-spin motion-reduce:animate-none"
                                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-20" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="3"></circle>
                                    <path class="opacity-95" fill="none" stroke="currentColor" stroke-width="3"
                                          stroke-linecap="round" stroke-dasharray="62 44"
                                          d="M12 2a10 10 0 0 1 10 10"></path>
                                </svg>
                            </div>
                        </div>

                        <h1 class="mt-6 font-display text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white
                                   [text-wrap:balance]">
                            Processing your payment
                        </h1>

                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed [text-wrap:pretty]">
                            We're confirming your payment with PayMongo.
                            This usually takes a few seconds.
                        </p>

                        @if($secondsRemaining !== null && $secondsRemaining > 0)
                            <div x-data="{
                                    remaining: {{ $secondsRemaining }},
                                    _timer: null,
                                    get display() {
                                        const m = Math.floor(this.remaining / 60);
                                        const s = this.remaining % 60;
                                        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                                    },
                                    get isUrgent() {
                                        return this.remaining > 0 && this.remaining <= 60;
                                    },
                                    init() {
                                        if (this.remaining <= 0) return;
                                        this._timer = setInterval(() => {
                                            this.remaining = Math.max(0, this.remaining - 1);
                                            if (this.remaining === 0) {
                                                clearInterval(this._timer);
                                                this._timer = null;
                                            }
                                        }, 1000);
                                    },
                                    destroy() {
                                        if (this._timer) { clearInterval(this._timer); this._timer = null; }
                                    }
                                 }"
                                 :class="isUrgent
                                     ? 'border-amber-200 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/8'
                                     : 'border-gray-200 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/60'"
                                 class="mt-6 inline-flex flex-col items-center gap-1 px-6 py-4
                                        rounded-2xl border
                                        transition-colors duration-500 motion-reduce:transition-none">
                                <span class="text-[10px] font-bold uppercase tracking-[0.22em]"
                                      :class="isUrgent
                                          ? 'text-amber-700 dark:text-amber-300'
                                          : 'text-gray-500 dark:text-gray-400'">
                                    Time remaining
                                </span>
                                <span class="font-display text-4xl sm:text-5xl font-bold tabular-nums leading-none tracking-tight"
                                      aria-live="off"
                                      :class="isUrgent
                                          ? 'text-amber-700 dark:text-amber-300'
                                          : 'text-gray-900 dark:text-white'"
                                      x-text="display"></span>
                                <span class="text-[10px] uppercase tracking-wider"
                                      :class="isUrgent
                                          ? 'text-amber-600/90 dark:text-amber-400/90'
                                          : 'text-gray-400 dark:text-gray-500'">
                                    Until window closes
                                </span>
                            </div>
                        @endif
                    </div>

                @endif

                <dl class="mt-6 divide-y divide-gray-100 dark:divide-gray-800
                           bg-gray-50 dark:bg-gray-900/60
                           border border-gray-200 dark:border-gray-800
                           rounded-2xl overflow-hidden text-left">
                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">
                            Reference
                        </dt>
                        <dd class="text-xs sm:text-sm font-mono font-bold text-gray-900 dark:text-white
                                   truncate tabular-nums">
                            {{ $booking->booking_reference }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">
                            Amount
                        </dt>
                        <dd class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">
                            ₱{{ number_format((float) $booking->total_amount, 2) }}
                        </dd>
                    </div>
                </dl>

                @if(!$deadlinePassed)
                    <div class="mt-6">
                        <button type="button"
                                wire:click="forceCheck"
                                wire:loading.attr="disabled"
                                wire:target="forceCheck"
                                class="inline-flex w-full items-center justify-center gap-2 h-12 px-6
                                       rounded-full bg-primary-600 hover:bg-primary-700 text-white
                                       font-semibold text-sm
                                       shadow-lg shadow-primary-500/25
                                       transition-all duration-200
                                       active:scale-[0.98]
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:pointer-events-none
                                       hover:-translate-y-0.5">
                            <span wire:loading.remove wire:target="forceCheck" class="inline-flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Check Status Now
                            </span>
                            <span wire:loading wire:target="forceCheck" class="inline-flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin h-4 w-4 text-white motion-reduce:animate-none"
                                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Checking…
                            </span>
                        </button>

                        <div class="mt-4 flex items-start gap-2.5
                                    bg-emerald-50/70 dark:bg-emerald-500/8
                                    border border-emerald-200/70 dark:border-emerald-500/25
                                    rounded-xl px-3.5 py-3">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="w-4 h-4 shrink-0 mt-0.5 text-emerald-600 dark:text-emerald-400"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <p class="text-xs text-emerald-800 dark:text-emerald-300 leading-relaxed">
                                You can safely close this tab — we'll email you the moment your payment is confirmed.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="mt-6 text-center">
                    <a href="{{ route('my-bookings') }}" wire:navigate
                       class="relative inline-flex items-center gap-1.5 text-sm font-semibold
                              text-gray-500 dark:text-gray-400
                              hover:text-primary-600 dark:hover:text-primary-400
                              transition-colors
                              active:scale-95
                              before:absolute before:content-[''] before:-inset-2 before:rounded
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        {{ $deadlinePassed ? 'Go to My Bookings' : 'Return to My Bookings' }}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>

                @if(config('app.debug') && $lastCheckedReference)
                    <div class="mt-6 pt-4 border-t border-dashed border-gray-200 dark:border-gray-800 text-left">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-600 mb-2">
                            Debug · visible when APP_DEBUG=true
                        </p>
                        <dl class="space-y-1 font-mono text-xs">
                            <div class="flex items-baseline gap-2">
                                <dt class="text-gray-400 dark:text-gray-600 shrink-0">session:</dt>
                                <dd class="text-gray-500 dark:text-gray-400 break-all">{{ $lastCheckedReference }}</dd>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <dt class="text-gray-400 dark:text-gray-600 shrink-0">status:</dt>
                                <dd class="{{ $lastGatewayStatus === 'paid'
                                        ? 'text-emerald-600 dark:text-emerald-400 font-bold'
                                        : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $lastGatewayStatus ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <dt class="text-gray-400 dark:text-gray-600 shrink-0">payments[]:</dt>
                                <dd class="{{ ($lastPaymentCount ?? 0) > 0
                                        ? 'text-emerald-600 dark:text-emerald-400 font-bold'
                                        : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $lastPaymentCount ?? 0 }} {{ ($lastPaymentCount ?? 0) === 1 ? 'entry' : 'entries' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>