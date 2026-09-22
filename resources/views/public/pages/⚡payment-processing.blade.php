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

    public Booking $booking;

    #[Locked]
    public bool $deadlinePassed = false;

    #[Locked]
    public int $deadlineMinutes = Booking::PAYMENT_DEADLINE_MINUTES;

    #[Locked]
    public ?string $lastGatewayStatus = null;

    #[Locked]
    public ?string $lastCheckedReference = null;

    #[Locked]
    public ?int $lastPaymentCount = null;

    public function mount($bookingId): void
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->findOrFail((int) $bookingId);

        abort_unless(Auth::id() === $booking->user_id, 403);

        $this->booking        = $booking;
        $this->deadlinePassed = $this->computeDeadlinePassed();
    }

    /**
     * Livewire re-hydrates the bound Booking by ID on every subsequent
     * request. Re-verify ownership and recompute the deadline on every
     * request — the client-dehydrated `deadlinePassed` cannot be trusted.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::id() === $this->booking->user_id, 403);

        $this->deadlinePassed = $this->computeDeadlinePassed();
    }

    public function checkStatus(PayMongoService $payMongo)
    {
        $this->booking->refresh();

        if ($this->isFinalised()) {
            session()->flash('message', 'Payment successful! Your booking is confirmed.');
            return $this->redirectToBookings();
        }

        if ($this->booking->status === Booking::STATUS_CANCELLED) {
            session()->flash('error', 'This booking was cancelled because payment was not completed in time.');
            return $this->redirectToBookings();
        }

        if ($this->computeDeadlinePassed()) {
            $this->deadlinePassed = true;
            return null;
        }

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
                // Session has a successful payment — finalize via the service.
                // The service does the DB work in a transaction with row
                // locks (Golden Rules #4, #11).
                $payMongo->finalizeCheckoutSession($reference);
            }
        }

        $this->booking->refresh();

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

            // Signal 1: session-level status.
            $isPaid = in_array($status, ['paid', 'succeeded'], true);

            // Signal 2: any payment inside the session marked paid.
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

<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 py-12 px-4 sm:px-6 lg:px-8"
     @if(!$deadlinePassed) wire:poll.5s="checkStatus" @endif>

    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden">
            <div class="p-6 sm:p-8 text-center">

                @if($deadlinePassed)
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-600 dark:text-amber-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>

                    <h1 class="mt-6 text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                        Payment window has closed
                    </h1>

                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        We haven't received a payment confirmation within the
                        {{ $deadlineMinutes }}-minute window. The booking will
                        be released shortly.
                    </p>
                @else
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-600 dark:text-primary-400 animate-spin motion-reduce:animate-none"
                             fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <h1 class="mt-6 text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                        Processing your payment…
                    </h1>

                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        Please wait while we confirm your payment with PayMongo.
                        This usually takes a few seconds.
                    </p>
                @endif

                <div class="mt-6 flex flex-col items-center gap-3">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ref</span>
                        <span class="text-sm font-mono font-bold text-gray-900 dark:text-white">{{ $booking->booking_reference }}</span>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Amount:
                        <span class="font-semibold text-gray-900 dark:text-white">
                            ₱{{ number_format((float) $booking->total_amount, 2) }}
                        </span>
                    </p>
                </div>

                @if(!$deadlinePassed)
                    <div class="mt-8">
                        <button type="button"
                                wire:click="forceCheck"
                                wire:loading.attr="disabled"
                                wire:target="forceCheck"
                                class="btn-primary w-full sm:w-auto active:scale-95 transition
                                       inline-flex items-center justify-center gap-2
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       disabled:opacity-60 disabled:pointer-events-none">
                            <span wire:loading.remove wire:target="forceCheck">Check Status Now</span>
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
                    </div>
                @endif

                <div class="mt-4">
                    <a href="{{ route('my-bookings') }}" wire:navigate
                       class="inline-flex items-center gap-1 text-sm font-semibold
                              text-gray-500 dark:text-gray-400
                              hover:text-primary-600 dark:hover:text-primary-400
                              transition active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        {{ $deadlinePassed ? 'Go to My Bookings' : 'Skip and go to My Bookings' }}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>

                {{-- ── DEV DIAGNOSTIC ──────────────────────────────────── --}}
                @if(config('app.debug') && $lastCheckedReference)
                    <div class="mt-6 pt-4 border-t border-dashed border-gray-200 dark:border-gray-700 text-left">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">
                            Debug (visible in APP_DEBUG only)
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono break-all">
                            <span class="text-gray-400">session:</span> {{ $lastCheckedReference }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                            <span class="text-gray-400">status:</span>
                            <span class="{{ $lastGatewayStatus === 'paid' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $lastGatewayStatus ?? '—' }}
                            </span>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                            <span class="text-gray-400">payments[]:</span>
                            <span class="{{ ($lastPaymentCount ?? 0) > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-gray-500' }}">
                                {{ $lastPaymentCount ?? 0 }} entr{{ ($lastPaymentCount ?? 0) === 1 ? 'y' : 'ies' }}
                            </span>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>